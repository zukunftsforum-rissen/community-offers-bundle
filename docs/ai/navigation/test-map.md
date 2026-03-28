# Test Map

This file lists important tests that describe runtime behavior.

Tests often define the real functional specification of the system.

---

# Core Workflow Tests

DeviceControllerTest  
Validates device poll and confirm logic.

DoorWorkflowCorrelationTest  
Validates correlationId propagation across workflow steps.

DoorJobServiceTest  
Validates workflow state transitions.

AccessServiceTest  
Validates mapping of areas to groups.

SimulatorDoorGatewayTest  
Validates emulator gateway behavior.

---

# Recommended Reading Order

When analyzing system behavior:

1. DeviceControllerTest
2. DoorJobServiceTest
3. DoorWorkflowCorrelationTest
4. AccessServiceTest
5. SimulatorDoorGatewayTest

---

# Notes

If new major tests are added,
especially workflow-related tests,
they should be listed here.

This file improves:

AI-assisted navigation  
debugging workflow understanding  
test-driven system comprehension
